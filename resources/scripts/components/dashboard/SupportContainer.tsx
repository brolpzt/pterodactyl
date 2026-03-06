import React from 'react';
import PageContentBlock from '@/components/elements/PageContentBlock';
import tw from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faPlus } from '@fortawesome/free-solid-svg-icons';
import Button from '@/components/elements/Button';
import GreyRowBox from '@/components/elements/GreyRowBox';
import { Link, useHistory } from 'react-router-dom';
import { useTickets } from '@/api/account/tickets';
import Spinner from '@/components/elements/Spinner';
import { formatDistanceToNow } from 'date-fns';
import styled from 'styled-components/macro';

const Container = styled.div`
    ${tw`flex flex-wrap flex-col md:flex-row shadow-none bg-transparent p-0`};

    & > a {
        ${tw`w-full no-underline`};
    }
`;

export default () => {
    const history = useHistory();
    const { data: tickets, error } = useTickets();

    return (
        <PageContentBlock title={'Support Tickets'} showFlashKey={'support'}>
            <div css={tw`flex items-center justify-between mb-10`}>
                <div>
                    <h1 css={tw`text-2xl`}>Support Tickets</h1>
                    <p css={tw`text-neutral-400 text-sm`}>Manage your support requests and get help.</p>
                </div>
                <Link to={'/account/support/new'}>
                    <Button color={'primary'}>
                        <FontAwesomeIcon icon={faPlus} css={tw`mr-2`} />
                        New Ticket
                    </Button>
                </Link>
            </div>

            {!tickets && !error ? (
                <Spinner size={'large'} centered />
            ) : (
                <>
                    {tickets?.length === 0 ? (
                        <p css={tw`text-center text-neutral-400`}>You do not have any support tickets.</p>
                    ) : (
                        <Container>
                            {tickets?.map((ticket) => (
                                <GreyRowBox
                                    key={ticket.id}
                                    onClick={() => history.push(`/account/support/${ticket.id}`)}
                                    css={tw`flex items-center mb-2 cursor-pointer transition-colors duration-150 hover:border-neutral-500`}
                                >
                                    <div css={tw`flex-1`}>
                                        <p css={tw`text-lg mb-0`}>{ticket.subject}</p>
                                        <p css={tw`text-sm text-neutral-400 mb-0`}>
                                            #{ticket.id} &bull; {ticket.department} {ticket.serverName ? ` &bull; ${ticket.serverName}` : ''}
                                        </p>
                                    </div>
                                    <div css={tw`text-right ml-4`}>
                                        <span
                                            css={[
                                                tw`px-2 py-1 rounded text-[10px] uppercase font-bold`,
                                                ticket.status === 'open' ? tw`bg-yellow-500 text-yellow-100` : tw`bg-green-500 text-green-100`,
                                            ]}
                                        >
                                            {ticket.status}
                                        </span>
                                        <p css={tw`text-xs text-neutral-500 mt-2`}>
                                            {formatDistanceToNow(ticket.updatedAt, { addSuffix: true })}
                                        </p>
                                    </div>
                                </GreyRowBox>
                            ))}
                        </Container>
                    )}
                </>
            )}
        </PageContentBlock>
    );
};
